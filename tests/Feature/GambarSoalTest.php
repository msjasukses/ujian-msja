<?php

namespace Tests\Feature;

use App\Models\Soal;
use App\Models\User;
use App\Services\GambarSoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as GambarLembar;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Gambar pada butir soal: disisipkan lewat formulir, ditempel dari papan klip,
 * dan ditarik dari berkas Word maupun Excel yang diimpor.
 */
class GambarSoalTest extends TestCase
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
    // Unggahan dari formulir
    // =====================================================================

    public function test_gambar_diunggah_dan_alamatnya_dikembalikan(): void
    {
        $jawab = $this->post(route('soal.gambar'), [
            'gambar' => $this->berkasGambar('grafik.png'),
        ]);

        $jawab->assertOk();
        $url = $jawab->json('url');

        $this->assertStringStartsWith('/storage/soal/', $url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
    }

    public function test_gambar_yang_sama_tidak_disimpan_dua_kali(): void
    {
        $satu = $this->post(route('soal.gambar'), ['gambar' => $this->berkasGambar('a.png')])->json('url');
        $dua = $this->post(route('soal.gambar'), ['gambar' => $this->berkasGambar('b.png')])->json('url');

        // Nama berkas diambil dari sidik jari isinya, jadi isi yang identik
        // bermuara ke berkas yang sama meski nama unggahannya berbeda.
        $this->assertSame($satu, $dua);
        $this->assertCount(1, Storage::disk('public')->allFiles('soal'));
    }

    public function test_berkas_yang_bukan_gambar_ditolak(): void
    {
        $palsu = UploadedFile::fake()->createWithContent('gambar.png', '<?php echo "halo";');

        $this->post(route('soal.gambar'), ['gambar' => $palsu])
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Berkas yang diunggah bukan gambar yang dikenali (JPG, PNG, GIF, atau WebP).');

        $this->assertSame([], Storage::disk('public')->allFiles('soal'));
    }

    /** Akhiran berkas ditentukan isinya, bukan nama yang dikirim pengunggah. */
    public function test_akhiran_berkas_mengikuti_jenis_gambar_sebenarnya(): void
    {
        $url = $this->post(route('soal.gambar'), [
            // Isi PNG tetapi dinamai .jpg oleh pengirim.
            'gambar' => UploadedFile::fake()->createWithContent('tipuan.jpg', $this->png(40, 30)),
        ])->json('url');

        $this->assertStringEndsWith('.png', $url);
    }

    public function test_gambar_lebar_diperkecil(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }

        $url = $this->post(route('soal.gambar'), [
            'gambar' => UploadedFile::fake()->createWithContent('besar.png', $this->png(2400, 1200)),
        ])->json('url');

        $info = getimagesizefromstring(Storage::disk('public')->get(str_replace('/storage/', '', $url)));

        $this->assertSame(GambarSoalService::MAKS_LEBAR, $info[0]);
        // Rasionya ikut terjaga, gambar tidak menjadi gepeng.
        $this->assertSame((int) round(GambarSoalService::MAKS_LEBAR / 2), $info[1]);
    }

    // =====================================================================
    // Gambar pada butir soal yang tersimpan
    // =====================================================================

    public function test_gambar_tampil_di_halaman_soal_dan_lolos_penyaring(): void
    {
        $url = $this->post(route('soal.gambar'), ['gambar' => $this->berkasGambar('peta.png')])->json('url');

        $this->post(route('soal.store'), [
            'jenis' => Soal::PG,
            'pertanyaan' => 'Perhatikan peta berikut. <img src="'.$url.'" alt="Gambar soal"> Kota yang ditandai adalah ...',
            'bobot' => 1,
            'opsi_text' => ['Bandung', 'Semarang'],
            'kunci_pg' => ['A'],
        ])->assertRedirect();

        $soal = Soal::latest('id')->firstOrFail();

        $this->get(route('soal.show', $soal))
            ->assertOk()
            ->assertSee('src="'.$url.'"', false);
    }

    // =====================================================================
    // Impor Word
    // =====================================================================

    public function test_impor_word_menarik_gambar_yang_tertanam(): void
    {
        $path = $this->berkasWord(function ($section) {
            $section->addText('1. Perhatikan grafik berikut. Nilai tertingginya adalah ...');
            $section->addImage($this->berkasGambarSementara());
            $section->addText('A. 10');
            $section->addText('B. 20');
            $section->addText('KUNCI: B');
        });

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->simpanSeluruhPratinjau();

        $soal = Soal::latest('id')->firstOrFail();

        $this->assertStringContainsString('<img src="/storage/soal/', $soal->pertanyaan);
        $this->assertStringContainsString('Nilai tertingginya', $soal->pertanyaan);
        $this->assertNotSame([], Storage::disk('public')->allFiles('soal'));
    }

    /** Gambar yang menyusul sebuah opsi menjadi milik opsi itu, bukan pertanyaan. */
    public function test_impor_word_menempelkan_gambar_ke_opsi(): void
    {
        $path = $this->berkasWord(function ($section) {
            $section->addText('1. Bangun datar manakah yang punya empat sisi sama panjang?');
            $section->addText('A.');
            $section->addImage($this->berkasGambarSementara(30, 30));
            $section->addText('B. Segitiga');
            $section->addText('KUNCI: A');
        });

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->simpanSeluruhPratinjau();

        $soal = Soal::latest('id')->firstOrFail();

        $this->assertStringContainsString('<img src="/storage/soal/', $soal->opsi[0]['text']);
        $this->assertStringNotContainsString('<img', $soal->pertanyaan);
        $this->assertSame('Segitiga', $soal->opsi[1]['text']);
    }

    // =====================================================================
    // Impor Excel
    // =====================================================================

    public function test_impor_excel_menarik_gambar_menurut_sel_tempatnya(): void
    {
        $path = $this->berkasExcelBergambar();

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'excel',
            'berkas' => new UploadedFile($path, 'bank.xlsx', null, null, true),
        ])->assertRedirect();

        $this->simpanSeluruhPratinjau();

        $soal = Soal::latest('id')->firstOrFail();

        // Gambar di kolom B menjadi bagian pertanyaan, gambar di kolom C
        // (opsi_a) menjadi bagian opsi A.
        $this->assertStringContainsString('<img src="/storage/soal/', $soal->pertanyaan);
        $this->assertStringContainsString('Perhatikan gambar', $soal->pertanyaan);
        $this->assertStringContainsString('<img src="/storage/soal/', $soal->opsi[0]['text']);
        $this->assertStringNotContainsString('<img', $soal->opsi[1]['text']);
    }

    // =====================================================================
    // Pembersihan berkas menganggur
    // =====================================================================

    public function test_perintah_bersihkan_hanya_menghapus_gambar_yang_tak_dirujuk(): void
    {
        $dipakaiPertanyaan = $this->post(route('soal.gambar'), ['gambar' => $this->berkasGambar('a.png')])->json('url');
        $dipakaiOpsi = $this->post(route('soal.gambar'), [
            'gambar' => UploadedFile::fake()->createWithContent('b.png', $this->png(50, 50)),
        ])->json('url');
        $menganggur = $this->post(route('soal.gambar'), [
            'gambar' => UploadedFile::fake()->createWithContent('c.png', $this->png(70, 20)),
        ])->json('url');

        Soal::create([
            'jenis' => Soal::PG,
            'pertanyaan' => 'Perhatikan <img src="'.$dipakaiPertanyaan.'" alt="Gambar soal"> berikut.',
            'opsi' => [
                ['key' => 'A', 'text' => '<img src="'.$dipakaiOpsi.'" alt="Gambar soal">'],
                ['key' => 'B', 'text' => 'Segitiga'],
            ],
            'kunci' => ['A'],
            'bobot' => 1,
            'is_aktif' => true,
        ]);

        $disk = Storage::disk('public');
        $this->assertCount(3, $disk->allFiles('soal'));

        // Tanpa --hapus tidak ada yang hilang.
        $this->artisan('soal:bersihkan-gambar')->assertSuccessful();
        $this->assertCount(3, $disk->allFiles('soal'));

        $this->artisan('soal:bersihkan-gambar --hapus')->assertSuccessful();

        $disk->assertExists(str_replace('/storage/', '', $dipakaiPertanyaan));
        // Rujukan di dalam opsi tersimpan sebagai JSON — ini yang paling
        // gampang terlewat dan membuat gambar terpakai ikut terhapus.
        $disk->assertExists(str_replace('/storage/', '', $dipakaiOpsi));
        $disk->assertMissing(str_replace('/storage/', '', $menganggur));
    }

    // =====================================================================
    // Pembantu
    // =====================================================================

    /** Buat berkas PNG polos berukuran tertentu sebagai isi biner. */
    protected function png(int $lebar = 60, int $tinggi = 40): string
    {
        $img = imagecreatetruecolor($lebar, $tinggi);
        imagefilledrectangle($img, 0, 0, $lebar, $tinggi, imagecolorallocate($img, 30, 90, 200));

        ob_start();
        imagepng($img);
        $biner = ob_get_clean();
        imagedestroy($img);

        return $biner;
    }

    protected function berkasGambar(string $nama): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $this->png());
    }

    /** PNG nyata di disk, untuk disisipkan ke dokumen Word/Excel. */
    protected function berkasGambarSementara(int $lebar = 60, int $tinggi = 40): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gbr').'.png';
        file_put_contents($path, $this->png($lebar, $tinggi));

        return $path;
    }

    protected function berkasWord(callable $isi): string
    {
        $phpWord = new PhpWord;
        $isi($phpWord->addSection());

        $path = tempnam(sys_get_temp_dir(), 'uji').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    protected function berkasExcelBergambar(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();

        $baris = [
            ['jenis', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'bobot'],
            ['pg', 'Perhatikan gambar berikut.', '', 'Segitiga', '', '', '', 'A', 1],
        ];

        foreach ($baris as $i => $kolom) {
            foreach (array_values($kolom) as $j => $nilai) {
                $sheet->setCellValue([$j + 1, $i + 1], $nilai);
            }
        }

        foreach (['B2', 'C2'] as $sel) {
            $gambar = new GambarLembar;
            $gambar->setPath($this->berkasGambarSementara());
            $gambar->setCoordinates($sel);
            $gambar->setWorksheet($sheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /**
     * Tempuh alur simpan sebagaimana guru melakukannya: buka halaman
     * pratinjau lebih dulu, baru tekan Simpan.
     *
     * Langkah "buka halaman" itu bukan hiasan — pratinjau sempat dititipkan
     * sebagai flash session yang habis begitu halamannya digambar, sehingga
     * penekanan tombol Simpan selalu ditolak. Menyuntikkan ulang isi session
     * di dalam tes akan menyembunyikan persis kegagalan itu.
     */
    protected function simpanSeluruhPratinjau(): void
    {
        $pratinjau = session('import_soal_pratinjau');
        $this->assertNotNull($pratinjau, 'Pratinjau tidak tersimpan setelah berkas diunggah.');

        $this->get(route('soal.import.form'))->assertOk();

        $this->post('/soal/import/simpan', ['pilih' => array_keys($pratinjau['soal'])])
            ->assertRedirect(route('soal.index'));
    }
}

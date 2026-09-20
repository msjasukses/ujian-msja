<?php

namespace Tests\Feature;

use App\Models\PaketSoal;
use App\Models\PaketSoalDetail;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\Ujian;
use App\Models\UjianPeserta;
use App\Models\User;
use App\Services\ImportSoalExcelService;
use App\Services\ImportSoalWordService;
use App\Services\PengerjaanUjianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/** Import bank soal dari Word & Excel, serta seluruh tombol Export Excel. */
class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'uji@ujian.test', 'password' => 'rahasia123',
            'role' => User::ROLE_ADMIN, 'is_aktif' => true,
        ]);

        $this->actingAs($this->admin);
    }

    /**
     * Pratinjau harus bertahan sampai tombol Simpan ditekan.
     *
     * Data pratinjau sempat dititipkan sebagai flash session, yang hanya
     * bertahan satu permintaan: begitu halaman pratinjaunya selesai digambar,
     * datanya hilang, dan penekanan Simpan sesudahnya selalu dijawab
     * "pratinjau sudah kedaluwarsa". Tes ini menempuh ketiga langkahnya
     * berurutan seperti yang dilakukan guru.
     */
    public function test_pratinjau_bertahan_sampai_disimpan(): void
    {
        $path = $this->berkasWord([
            '1. Soal pertama', 'A. a', 'B. b', 'JAWABAN: A',
            '2. Soal kedua', 'A. a', 'B. b', 'JAWABAN: B',
        ]);

        // Langkah 1 — unggah berkas.
        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect(route('soal.import.form', ['sumber' => 'word']));

        // Langkah 2 — halaman pratinjau digambar. Inilah permintaan yang dulu
        // menghabiskan flash session.
        $this->get(route('soal.import.form', ['sumber' => 'word']))
            ->assertOk()
            ->assertSee('Soal pertama')
            ->assertSee('Soal kedua');

        // Pratinjau masih ada sesudah halamannya dibuka.
        $this->assertNotNull(session('import_soal_pratinjau'));

        // Langkah 3 — tekan Simpan.
        $this->post('/soal/import/simpan', ['pilih' => [0, 1]])
            ->assertRedirect(route('soal.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Soal::count());
        // Setelah tersimpan, pratinjaunya dibersihkan.
        $this->assertNull(session('import_soal_pratinjau'));
    }

    /** Halaman pratinjau boleh disegarkan berkali-kali tanpa kehilangan data. */
    public function test_pratinjau_bertahan_meski_halaman_disegarkan(): void
    {
        $path = $this->berkasWord(['1. Soal uji', 'A. a', 'B. b', 'JAWABAN: A']);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        foreach (range(1, 3) as $ulang) {
            $this->get(route('soal.import.form'))->assertOk()->assertSee('Soal uji');
        }

        $this->post('/soal/import/simpan', ['pilih' => [0]])->assertRedirect(route('soal.index'));

        $this->assertSame(1, Soal::count());
    }

    /** Tombol Batal membuang pratinjaunya. */
    public function test_batal_membuang_pratinjau(): void
    {
        $path = $this->berkasWord(['1. Soal uji', 'A. a', 'B. b', 'JAWABAN: A']);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        $this->post('/soal/import/batal')->assertRedirect(route('soal.import.form'));

        $this->assertNull(session('import_soal_pratinjau'));
        $this->post('/soal/import/simpan', ['pilih' => [0]])
            ->assertRedirect(route('soal.import.form'))
            ->assertSessionHas('error');
    }

    /**
     * Rumus LaTeX pada naskah Word diurai saat diimpor.
     *
     * Pengurai di sisi peramban hanya melayani tempelan ke formulir; berkas
     * yang diunggah tidak melewatinya sama sekali. Tanpa pengurai di sisi
     * server, naskah bertuliskan "\(\frac{11}{4}\)" masuk ke bank soal sebagai
     * kode mentah dan terbaca apa adanya oleh siswa.
     */
    public function test_impor_word_mengurai_rumus_latex(): void
    {
        $path = $this->berkasWord([
            'Bentuk pecahan biasa dari \(\frac{11}{4}\) jika diubah ke pecahan campuran adalah...',
            'A. \(2 \frac{1}{4}\)',
            'B. \(2 \frac{3}{4}\)',
            'C. \(3 \frac{1}{4}\)',
            'D. \(3 \frac{3}{4}\)',
            'JAWABAN: B',
        ], nomorOtomatis: true);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect();

        // Pratinjau sudah menampilkan rumusnya, bukan kodenya. Isi soalnya
        // diperiksa lewat session, bukan lewat badan halaman: halaman itu
        // memuat kode pengurai di sisi peramban, yang sendirinya mengandung
        // tulisan "\frac" dan membuat pemeriksaan atas seluruh halaman selalu
        // menemukannya.
        $this->get(route('soal.import.form'))
            ->assertOk()
            ->assertSee('class="pecahan"', false);

        $this->assertStringNotContainsString(
            '\frac',
            session('import_soal_pratinjau')['soal'][0]['pertanyaan']
        );

        $this->post('/soal/import/simpan', ['pilih' => [0]])->assertRedirect(route('soal.index'));

        $soal = Soal::firstOrFail();

        $this->assertStringContainsString('class="pembilang">11<', $soal->pertanyaan);
        $this->assertStringContainsString('class="penyebut">4<', $soal->pertanyaan);
        $this->assertStringNotContainsString('\frac', $soal->pertanyaan);

        // Opsi jawaban ikut terurai.
        $this->assertStringContainsString('class="pecahan"', $soal->opsi[1]['text']);
        $this->assertStringNotContainsString('\frac', $soal->opsi[1]['text']);
    }

    /** Rumus LaTeX pada lembar Excel juga diurai. */
    public function test_impor_excel_mengurai_rumus_latex(): void
    {
        $path = $this->berkasExcel([
            ['jenis', 'pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'bobot'],
            ['pg', 'Hitung \(\sqrt{144} \times 2\) adalah ...', '\(12\)', '24', '\(\frac{1}{2}\)', '6', '', 'B', 1],
        ]);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'excel',
            'berkas' => new UploadedFile($path, 'bank.xlsx', null, null, true),
        ])->assertRedirect();

        $this->post('/soal/import/simpan', ['pilih' => [0]])->assertRedirect(route('soal.index'));

        $soal = Soal::firstOrFail();

        $this->assertStringContainsString('class="akar"', $soal->pertanyaan);
        $this->assertStringContainsString('×', $soal->pertanyaan);
        $this->assertStringNotContainsString('\sqrt', $soal->pertanyaan);
        $this->assertStringContainsString('class="pecahan"', $soal->opsi[2]['text']);
    }

    /** Naskah Word dengan kelima jenis soal terbaca dengan benar. */
    public function test_parser_word_mengenali_semua_jenis_soal(): void
    {
        $baris = [
            '1. Ibu kota Provinsi Jawa Barat adalah ...',
            'A. Bandung', 'B. Semarang', 'C. Surabaya', 'D. Serang',
            'JAWABAN: A', 'BOBOT: 2', 'LEVEL: C1', 'KESUKARAN: mudah',
            'PEMBAHASAN: Bandung ibu kota Jawa Barat.',

            '2. Manakah bilangan prima?',
            'A. 2', 'B. 4', 'C. 7', 'D. 9',
            'JAWABAN: A, C',

            '3. Air mendidih pada suhu 100 derajat Celsius.',
            'JAWABAN: benar',

            '4. Jelaskan proses terjadinya hujan!',
            'JENIS: essay',
            'JAWABAN: Evaporasi, kondensasi, lalu presipitasi.',
            'BOBOT: 5',

            '5. Jodohkan negara dengan ibu kotanya!',
            'Jepang ## Tokyo',
            'Korea Selatan ## Seoul',
            'Thailand ## Bangkok',
        ];

        $hasil = app(ImportSoalWordService::class)->baca($this->berkasWord($baris));

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(5, $hasil['soal']);

        [$pg, $pgk, $bs, $essay, $jodoh] = $hasil['soal'];

        $this->assertSame(Soal::PG, $pg['jenis']);
        $this->assertSame(['A'], $pg['kunci']);
        $this->assertSame(2.0, $pg['bobot']);
        $this->assertSame('C1', $pg['level_kognitif']);
        $this->assertSame('mudah', $pg['tingkat_kesukaran']);
        $this->assertStringContainsString('Bandung ibu kota', $pg['pembahasan']);
        $this->assertStringNotContainsString('JAWABAN', $pg['pertanyaan']);

        // Kunci lebih dari satu otomatis menjadi PG kompleks.
        $this->assertSame(Soal::PG_KOMPLEKS, $pgk['jenis']);
        $this->assertSame(['A', 'C'], $pgk['kunci']);

        $this->assertSame(Soal::BENAR_SALAH, $bs['jenis']);
        $this->assertSame(['benar'], $bs['kunci']);

        $this->assertSame(Soal::ESSAY, $essay['jenis']);
        $this->assertStringContainsString('Evaporasi', $essay['kunci']['jawaban']);

        $this->assertSame(Soal::PENJODOHAN, $jodoh['jenis']);
        $this->assertSame(['1' => 'A', '2' => 'B', '3' => 'C'], $jodoh['kunci']);
        $this->assertSame('Tokyo', $jodoh['opsi']['kanan'][0]['text']);
    }

    /** Soal Word tanpa baris kunci dilaporkan sebagai galat, bukan disimpan diam-diam. */
    public function test_parser_word_melaporkan_soal_tanpa_kunci(): void
    {
        $hasil = app(ImportSoalWordService::class)->baca($this->berkasWord([
            '1. Pertanyaan tanpa kunci',
            'A. satu', 'B. dua',
        ]));

        $this->assertSame([], $hasil['soal']);
        $this->assertCount(1, $hasil['galat']);
        $this->assertStringContainsString('KUNCI', $hasil['galat'][0]);
    }

    /** Alur import Word dua langkah: pratinjau lalu simpan yang dicentang. */
    public function test_import_word_lewat_halaman(): void
    {
        $path = $this->berkasWord([
            '1. Soal satu', 'A. a', 'B. b', 'JAWABAN: A',
            '2. Soal dua', 'A. a', 'B. b', 'JAWABAN: B',
        ]);

        $this->post('/soal/import/pratinjau', [
            'sumber' => 'word',
            'berkas' => new UploadedFile($path, 'naskah.docx', null, null, true),
        ])->assertRedirect(route('soal.import.form', ['sumber' => 'word']));

        $pratinjau = session('import_soal_pratinjau');
        $this->assertCount(2, $pratinjau['soal']);

        // Hanya butir pertama yang dicentang untuk disimpan.
        $this->withSession(['import_soal_pratinjau' => $pratinjau])
            ->post('/soal/import/simpan', ['pilih' => [0]])
            ->assertRedirect('/soal');

        $this->assertSame(1, Soal::count());
        $this->assertStringContainsString('Soal satu', Soal::first()->pertanyaan);
    }

    /** Berkas Excel dengan format template terbaca lengkap. */
    public function test_parser_excel_mengenali_semua_jenis_soal(): void
    {
        $baris = [
            ImportSoalExcelService::HEADER,
            ['pg', 'Ibu kota Jabar?', 'Bandung', 'Serang', '', '', '', 'A', 1, 'C1', 'mudah', 'Jelas.'],
            ['pg_kompleks', 'Bilangan prima?', '2', '4', '7', '', '', 'A,C', 2, 'C2', 'sedang', ''],
            ['benar_salah', 'Air mendidih 100 C.', '', '', '', '', '', 'benar', 1, '', '', ''],
            ['essay', 'Jelaskan hujan!', 'evaporasi, kondensasi', '', '', '', '', 'Uraian model', 5, 'C4', 'sedang', ''],
            ['penjodohan', 'Jodohkan!', 'Jepang ## Tokyo', 'Korea ## Seoul', '', '', '', '', 4, '', '', ''],
        ];

        $hasil = app(ImportSoalExcelService::class)->baca($this->berkasExcel($baris));

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(5, $hasil['soal']);

        [$pg, $pgk, $bs, $essay, $jodoh] = $hasil['soal'];

        $this->assertSame(['A'], $pg['kunci']);
        $this->assertSame(['A', 'C'], $pgk['kunci']);
        $this->assertSame(['benar'], $bs['kunci']);
        $this->assertSame(['evaporasi', 'kondensasi'], $essay['kunci']['kata_kunci']);
        $this->assertSame(['1' => 'A', '2' => 'B'], $jodoh['kunci']);
        $this->assertSame('Seoul', $jodoh['opsi']['kanan'][1]['text']);
    }

    /** Baris Excel yang cacat dilaporkan per nomor baris, sisanya tetap terbaca. */
    public function test_parser_excel_melaporkan_baris_bermasalah(): void
    {
        $hasil = app(ImportSoalExcelService::class)->baca($this->berkasExcel([
            ImportSoalExcelService::HEADER,
            ['pg', 'Soal baik', 'a', 'b', '', '', '', 'A', 1, '', '', ''],
            ['pg', 'Kunci di luar opsi', 'a', 'b', '', '', '', 'D', 1, '', '', ''],
            ['tidak_dikenal', 'Jenis salah', 'a', 'b', '', '', '', 'A', 1, '', '', ''],
            ['pg', '', 'a', 'b', '', '', '', 'A', 1, '', '', ''],
        ]));

        $this->assertCount(1, $hasil['soal']);
        $this->assertCount(3, $hasil['galat']);
        $this->assertStringContainsString('Baris 3', $hasil['galat'][0]);
        $this->assertStringContainsString('Baris 4', $hasil['galat'][1]);
        $this->assertStringContainsString('Baris 5', $hasil['galat'][2]);
    }

    /** Berkas template yang diunduh bisa langsung dibaca kembali oleh parser. */
    public function test_template_excel_bisa_diimpor_ulang(): void
    {
        $respons = $this->get('/soal/import/template-excel');
        $respons->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($tmp, $this->isiUnduhan($respons));

        $hasil = app(ImportSoalExcelService::class)->baca($tmp);

        // Lima contoh butir terbaca; blok petunjuk di bawahnya diabaikan sebagai galat.
        $this->assertCount(5, $hasil['soal']);
        @unlink($tmp);
    }

    public function test_template_word_bisa_diimpor_ulang(): void
    {
        $respons = $this->get('/soal/import/template-word');
        $respons->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        file_put_contents($tmp, $this->isiUnduhan($respons));

        $hasil = app(ImportSoalWordService::class)->baca($tmp);

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(5, $hasil['soal']);
        $this->assertSame(
            [Soal::PG, Soal::PG_KOMPLEKS, Soal::BENAR_SALAH, Soal::ESSAY, Soal::PENJODOHAN],
            array_column($hasil['soal'], 'jenis')
        );
        @unlink($tmp);
    }

    /** Semua tombol Export Excel menghasilkan berkas xlsx yang sah. */
    public function test_semua_export_excel_menghasilkan_berkas(): void
    {
        $ujian = $this->ujianContoh();

        $url = [
            '/topik/export',
            '/soal/export',
            '/log-login/export',
            '/referensi/siswa/export',
            "/paket-soal/{$ujian->paket_soal_id}/export",
            "/ujian/{$ujian->id}/peserta/export",
            "/monitoring/{$ujian->id}/export",
            "/laporan/nilai/{$ujian->id}/export",
            "/laporan/statistik/{$ujian->id}/export",
            "/laporan/analisis-butir/{$ujian->id}/export",
            "/laporan/remidial/{$ujian->id}/export",
            "/laporan/pengayaan/{$ujian->id}/export",
        ];

        foreach ($url as $u) {
            $respons = $this->get($u);
            $respons->assertOk();

            $isi = $this->isiUnduhan($respons);
            // Berkas xlsx adalah arsip zip — dua bita pertamanya selalu "PK".
            $this->assertSame('PK', substr($isi, 0, 2), "Berkas dari {$u} bukan xlsx yang sah.");
        }
    }

    // ---------------------------------------------------------------- bantu

    /** @param array<int, string> $baris */
    protected function berkasWord(array $baris, bool $nomorOtomatis = false): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        // Baris pertama diberi nomor bila naskahnya belum memuat penomoran.
        if ($nomorOtomatis && $baris !== []) {
            $baris[0] = '1. '.$baris[0];
        }

        foreach ($baris as $teks) {
            $section->addText($teks);
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /** @param array<int, array<int, mixed>> $baris */
    protected function berkasExcel(array $baris): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();

        foreach ($baris as $i => $kolom) {
            foreach (array_values($kolom) as $j => $nilai) {
                $sheet->setCellValue([$j + 1, $i + 1], $nilai);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    protected function isiUnduhan($respons): string
    {
        ob_start();
        $respons->baseResponse->sendContent();

        return ob_get_clean();
    }

    /** Satu ujian lengkap beserta peserta yang sudah selesai, untuk menguji export. */
    protected function ujianContoh(): Ujian
    {
        $siswa = Siswa::first();

        if (! $siswa) {
            $this->markTestSkipped('Database datacenter belum berisi data siswa.');
        }

        $paket = PaketSoal::create([
            'kode_paket' => 'EXP-1', 'nama_paket' => 'Paket Export', 'is_aktif' => true,
        ]);

        $soal = Soal::create([
            'jenis' => Soal::PG, 'pertanyaan' => 'Uji export?', 'bobot' => 1,
            'opsi' => [['key' => 'A', 'text' => 'ya'], ['key' => 'B', 'text' => 'tidak']],
            'kunci' => ['A'],
        ]);
        PaketSoalDetail::create([
            'paket_soal_id' => $paket->id, 'soal_id' => $soal->id, 'nomor_urut' => 1, 'bobot' => 1,
        ]);

        $ujian = Ujian::create([
            'kode_ujian' => 'EXP-UJN', 'nama_ujian' => 'Ujian Export',
            'paket_soal_id' => $paket->id,
            'waktu_mulai' => now()->subHour(), 'waktu_selesai' => now()->addHour(),
            'durasi_menit' => 60, 'kkm' => 75, 'status' => Ujian::AKTIF,
        ]);

        $peserta = UjianPeserta::create([
            'ujian_id' => $ujian->id, 'siswa_id' => $siswa->id,
            'status' => UjianPeserta::TERDAFTAR,
        ]);

        $pengerjaan = app(PengerjaanUjianService::class);
        $pengerjaan->mulai($peserta);
        $pengerjaan->simpanJawaban($peserta, $soal->id, ['A']);
        $pengerjaan->selesaikan($peserta);

        return $ujian;
    }
}
